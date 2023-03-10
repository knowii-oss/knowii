describe('client-ui: ForgotPasswordForm component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=forgotpasswordform--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to ForgotPasswordForm!');
  });
});
