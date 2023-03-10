describe('client-ui: PageHeader component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=pageheader--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to PageHeader!');
  });
});
