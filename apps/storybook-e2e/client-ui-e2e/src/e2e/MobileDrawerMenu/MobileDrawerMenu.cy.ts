describe('client-ui: MobileDrawerMenu component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=mobiledrawermenu--primary&args=isOpen:false;onClose;'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to MobileDrawerMenu!');
  });
});
