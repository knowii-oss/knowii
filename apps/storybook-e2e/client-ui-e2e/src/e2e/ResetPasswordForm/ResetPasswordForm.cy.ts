describe('client-ui: ResetPasswordForm component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=resetpasswordform--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to ResetPasswordForm!');
  });
});
